/*
Navicat MySQL Data Transfer

Source Server         : localost
Source Server Version : 50505
Source Host           : localhost:3306
Source Database       : managment

Target Server Type    : MYSQL
Target Server Version : 50505
File Encoding         : 65001

*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------

-- Records of group_statuses
-- ----------------------------
INSERT INTO `group_statuses` VALUES ('3', '3', '8', null, null, '1');
INSERT INTO `group_statuses` VALUES ('4', '3', '9', null, null, '2');
INSERT INTO `group_statuses` VALUES ('5', '4', '8', null, null, '1');
INSERT INTO `group_statuses` VALUES ('6', '4', '10', null, null, '2');
INSERT INTO `group_statuses` VALUES ('7', '5', '8', null, null, '2');
INSERT INTO `group_statuses` VALUES ('8', '5', '9', null, null, '1');
INSERT INTO `group_statuses` VALUES ('9', '5', '10', null, null, '1');
INSERT INTO `group_statuses` VALUES ('10', '5', '11', null, null, '1');
INSERT INTO `group_statuses` VALUES ('11', '6', '8', null, null, '1');
INSERT INTO `group_statuses` VALUES ('12', '6', '11', null, null, '2');
INSERT INTO `group_statuses` VALUES ('13', '7', '11', null, null, '1');
INSERT INTO `group_statuses` VALUES ('14', '7', '9', null, null, '2');
INSERT INTO `group_statuses` VALUES ('15', '15', '9', '2021-10-25 08:12:31', '2021-10-25 08:12:31', '1');
INSERT INTO `group_statuses` VALUES ('16', '15', '9', '2021-10-25 08:12:31', '2021-10-25 08:12:31', '2');
INSERT INTO `group_statuses` VALUES ('17', '8', '9', '2021-10-25 08:13:31', '2021-10-25 08:13:31', '1');
INSERT INTO `group_statuses` VALUES ('18', '8', '10', '2021-10-25 08:13:31', '2021-10-25 08:13:31', '2');
INSERT INTO `group_statuses` VALUES ('19', '9', '10', '2021-10-25 08:14:02', '2021-10-25 08:14:02', '1');
INSERT INTO `group_statuses` VALUES ('20', '9', '9', '2021-10-25 08:14:02', '2021-10-25 08:14:02', '2');
INSERT INTO `group_statuses` VALUES ('21', '10', '10', '2021-10-25 08:14:28', '2021-10-25 08:14:28', '1');
INSERT INTO `group_statuses` VALUES ('22', '10', '10', '2021-10-25 08:14:28', '2021-10-25 08:14:28', '2');
INSERT INTO `group_statuses` VALUES ('23', '16', '10', '2021-10-25 08:15:48', '2021-10-25 08:15:48', '1');
INSERT INTO `group_statuses` VALUES ('24', '16', '11', '2021-10-25 08:15:48', '2021-10-25 08:15:48', '2');
INSERT INTO `group_statuses` VALUES ('25', '2', '8', '2021-10-25 08:18:12', '2021-10-25 08:18:12', '1');
INSERT INTO `group_statuses` VALUES ('26', '2', '8', '2021-10-25 08:18:12', '2021-10-25 08:18:12', '2');
INSERT INTO `group_statuses` VALUES ('27', '1', '8', '2021-10-25 08:18:22', '2021-10-25 08:18:22', '1');
INSERT INTO `group_statuses` VALUES ('28', '1', '8', '2021-10-25 08:18:22', '2021-10-25 08:18:22', '2');
INSERT INTO `group_statuses` VALUES ('29', '12', '11', '2021-10-25 08:21:22', '2021-10-25 08:21:22', '1');
INSERT INTO `group_statuses` VALUES ('30', '12', '11', '2021-10-25 08:21:23', '2021-10-25 08:21:23', '2');
INSERT INTO `group_statuses` VALUES ('31', '11', '10', '2021-10-25 08:22:05', '2021-10-25 08:22:05', '1');
INSERT INTO `group_statuses` VALUES ('32', '11', '11', '2021-10-25 08:22:05', '2021-10-25 08:22:05', '1');
INSERT INTO `group_statuses` VALUES ('33', '11', '11', '2021-10-25 08:22:05', '2021-10-25 08:22:05', '2');
INSERT INTO `group_statuses` VALUES ('34', '17', '11', '2021-10-25 08:24:56', '2021-10-25 08:24:56', '1');

-- ----------------------------
-- Records of new_workflow
-- ----------------------------
INSERT INTO `new_workflow` VALUES ('1', '1', '1', '0', '0', null, '2021-10-25 06:48:01', '2021-10-25 07:20:50');
INSERT INTO `new_workflow` VALUES ('3', '2', '1', '1', '0', 'Estimation', '2021-10-25 07:19:38', '2021-10-25 09:45:52');
INSERT INTO `new_workflow` VALUES ('4', '4', '1', '0', '0', null, '2021-10-25 07:21:05', '2021-10-25 07:21:05');
INSERT INTO `new_workflow` VALUES ('5', '4', '1', '0', '1', null, '2021-10-25 07:21:26', '2021-10-25 07:21:26');
INSERT INTO `new_workflow` VALUES ('6', '3', '1', '0', '0', null, '2021-10-25 07:21:49', '2021-10-25 07:21:49');
INSERT INTO `new_workflow` VALUES ('7', '7', '1', '0', '1', null, '2021-10-25 07:22:08', '2021-10-25 07:22:08');
INSERT INTO `new_workflow` VALUES ('8', '6', '1', '0', '0', null, '2021-10-25 07:22:23', '2021-10-25 07:22:23');
INSERT INTO `new_workflow` VALUES ('9', '6', '1', '0', '1', null, '2021-10-25 07:22:42', '2021-10-25 07:22:42');
INSERT INTO `new_workflow` VALUES ('10', '5', '1', '1', '0', 'Estimation', '2021-10-25 07:23:24', '2021-10-25 07:23:24');
INSERT INTO `new_workflow` VALUES ('11', '7', '1', '0', '0', null, '2021-10-25 08:26:11', '2021-10-25 08:26:11');
INSERT INTO `new_workflow` VALUES ('12', '15', '1', '0', '0', null, '2021-10-25 08:26:38', '2021-10-25 08:26:38');
INSERT INTO `new_workflow` VALUES ('13', '8', '1', '1', '0', 'Technical Test', '2021-10-25 08:42:53', '2021-10-25 08:42:53');
INSERT INTO `new_workflow` VALUES ('14', '8', '1', '0', '1', null, '2021-10-25 08:43:22', '2021-10-25 08:43:22');
INSERT INTO `new_workflow` VALUES ('15', '9', '1', '0', '0', null, '2021-10-25 08:43:41', '2021-10-25 11:53:14');
INSERT INTO `new_workflow` VALUES ('16', '10', '1', '0', '0', null, '2021-10-25 08:44:03', '2021-10-25 08:44:03');
INSERT INTO `new_workflow` VALUES ('17', '16', '1', '0', '0', null, '2021-10-25 08:44:29', '2021-10-25 08:44:29');
INSERT INTO `new_workflow` VALUES ('18', '12', '1', '0', '0', null, '2021-10-25 08:44:45', '2021-10-25 08:44:45');
INSERT INTO `new_workflow` VALUES ('19', '11', '1', '0', '0', null, '2021-10-25 08:45:12', '2021-10-25 08:45:12');
INSERT INTO `new_workflow` VALUES ('20', '13', '1', '0', '0', null, '2021-10-25 08:45:40', '2021-10-25 08:45:40');
INSERT INTO `new_workflow` VALUES ('21', '1', '1', '0', '0', null, '2021-10-25 09:41:29', '2021-10-25 09:41:29');

-- ----------------------------
-- Records of new_workflow_statuses
-- ----------------------------
INSERT INTO `new_workflow_statuses` VALUES ('6', '1', '2', '1', '2021-10-25 07:20:50', '2021-10-25 07:20:50');
INSERT INTO `new_workflow_statuses` VALUES ('7', '4', '7', '1', '2021-10-25 07:21:06', '2021-10-25 07:21:06');
INSERT INTO `new_workflow_statuses` VALUES ('8', '5', '5', '0', '2021-10-25 07:21:26', '2021-10-25 07:21:26');
INSERT INTO `new_workflow_statuses` VALUES ('9', '6', '7', '1', '2021-10-25 07:21:49', '2021-10-25 07:21:49');
INSERT INTO `new_workflow_statuses` VALUES ('10', '7', '5', '0', '2021-10-25 07:22:08', '2021-10-25 07:22:08');
INSERT INTO `new_workflow_statuses` VALUES ('11', '8', '7', '1', '2021-10-25 07:22:23', '2021-10-25 07:22:23');
INSERT INTO `new_workflow_statuses` VALUES ('12', '9', '5', '0', '2021-10-25 07:22:42', '2021-10-25 07:22:42');
INSERT INTO `new_workflow_statuses` VALUES ('13', '10', '4', '1', '2021-10-25 07:23:24', '2021-10-25 07:23:24');
INSERT INTO `new_workflow_statuses` VALUES ('14', '10', '3', '1', '2021-10-25 07:23:24', '2021-10-25 07:23:24');
INSERT INTO `new_workflow_statuses` VALUES ('15', '10', '6', '1', '2021-10-25 07:23:24', '2021-10-25 07:23:24');
INSERT INTO `new_workflow_statuses` VALUES ('16', '11', '15', '1', '2021-10-25 08:26:11', '2021-10-25 08:26:11');
INSERT INTO `new_workflow_statuses` VALUES ('17', '12', '8', '1', '2021-10-25 08:26:38', '2021-10-25 08:26:38');
INSERT INTO `new_workflow_statuses` VALUES ('18', '13', '10', '1', '2021-10-25 08:42:53', '2021-10-25 08:42:53');
INSERT INTO `new_workflow_statuses` VALUES ('19', '13', '16', '1', '2021-10-25 08:42:53', '2021-10-25 08:42:53');
INSERT INTO `new_workflow_statuses` VALUES ('20', '14', '9', '0', '2021-10-25 08:43:22', '2021-10-25 08:43:22');
INSERT INTO `new_workflow_statuses` VALUES ('22', '16', '11', '1', '2021-10-25 08:44:03', '2021-10-25 08:44:03');
INSERT INTO `new_workflow_statuses` VALUES ('23', '17', '12', '1', '2021-10-25 08:44:29', '2021-10-25 08:44:29');
INSERT INTO `new_workflow_statuses` VALUES ('24', '18', '11', '1', '2021-10-25 08:44:45', '2021-10-25 08:44:45');
INSERT INTO `new_workflow_statuses` VALUES ('25', '19', '13', '1', '2021-10-25 08:45:12', '2021-10-25 08:45:12');
INSERT INTO `new_workflow_statuses` VALUES ('26', '20', '17', '1', '2021-10-25 08:45:40', '2021-10-25 08:45:40');
INSERT INTO `new_workflow_statuses` VALUES ('27', '21', '1', '1', '2021-10-25 09:41:30', '2021-10-25 09:41:30');
INSERT INTO `new_workflow_statuses` VALUES ('28', '3', '3', '1', '2021-10-25 09:45:52', '2021-10-25 09:45:52');
INSERT INTO `new_workflow_statuses` VALUES ('29', '3', '4', '1', '2021-10-25 09:45:52', '2021-10-25 09:45:52');
INSERT INTO `new_workflow_statuses` VALUES ('30', '3', '6', '1', '2021-10-25 09:45:52', '2021-10-25 09:45:52');
INSERT INTO `new_workflow_statuses` VALUES ('31', '15', '8', '1', '2021-10-25 11:53:14', '2021-10-25 11:53:14');

-- ----------------------------
-- Records of statuses
-- ----------------------------

INSERT INTO `statuses` VALUES ('1', 'Pending CAB', '1', '1', '1', null, '2021-10-25 08:18:22');
INSERT INTO `statuses` VALUES ('2', 'Pending Analysis', '1', '1', '1', null, '2021-10-25 08:18:12');
INSERT INTO `statuses` VALUES ('3', 'Design estimation', '2', '1', '2', null, null);
INSERT INTO `statuses` VALUES ('4', 'Technical estimation', '2', '1', '2', null, null);
INSERT INTO `statuses` VALUES ('5', 'Analysis Feedback', '1', '1', '1', null, null);
INSERT INTO `statuses` VALUES ('6', 'Testing Estimation', '2', '1', '2', null, null);
INSERT INTO `statuses` VALUES ('7', 'Pending Design', '3', '1', '1', null, null);
INSERT INTO `statuses` VALUES ('8', 'Pending implementation', '4', '1', '1', null, '2021-10-25 08:13:31');
INSERT INTO `statuses` VALUES ('9', 'Design feedback', '3', '1', '1', null, '2021-10-25 08:14:02');
INSERT INTO `statuses` VALUES ('10', 'Technical Implementation', '4', '1', '1', null, '2021-10-25 08:14:28');
INSERT INTO `statuses` VALUES ('11', 'Pending Testing', '5', '1', '1', null, '2021-10-25 08:22:05');
INSERT INTO `statuses` VALUES ('12', 'Test Case approval', '5', '1', '1', null, '2021-10-25 08:21:22');
INSERT INTO `statuses` VALUES ('13', 'Testing in progress', '5', '1', '1', null, '2021-10-25 11:37:13');
INSERT INTO `statuses` VALUES ('14', 'Pending rework', '4', '1', '1', null, null);
INSERT INTO `statuses` VALUES ('15', 'Design In Progress', '3', '1', '1', '2021-10-25 08:12:31', '2021-10-25 08:12:31');
INSERT INTO `statuses` VALUES ('16', 'Test case preparation', '5', '1', '1', '2021-10-25 08:15:48', '2021-10-25 08:15:48');
INSERT INTO `statuses` VALUES ('17', 'Pending Deployment', '5', '1', '1', '2021-10-25 08:24:56', '2021-10-25 08:24:56');
